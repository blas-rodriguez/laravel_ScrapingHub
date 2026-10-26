import { after, before, test } from "node:test";
import assert from "node:assert/strict";
import { mkdir } from "node:fs/promises";
import puppeteer from "puppeteer";
import { startTestApp } from "./server.mjs";

let app;
let browser;
before(async () => {
    app = await startTestApp();
    browser = await puppeteer.launch({ headless: true });
});
after(async () => {
    await browser?.close();
    app?.stop();
});

async function withPage(t, run) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setViewport({ width: 1440, height: 1000 });
    try {
        await run(page);
    } catch (error) {
        await mkdir("test-results", { recursive: true });
        await page
            .screenshot({
                path: `test-results/${t.name.replace(/[^a-z0-9]+/gi, "-")}.png`,
                fullPage: true,
            })
            .catch(() => {});
        throw error;
    } finally {
        await context.close();
    }
}

test("dashboard links to three independent modules", async (t) =>
    withPage(t, async (page) => {
        await page.goto("http://127.0.0.1:8012");
        assert.equal(
            await page.$$(".module-card").then((items) => items.length),
            3,
        );
        const links = await page.$$eval(".module-card", (cards) =>
            cards.map((card) => card.getAttribute("href")),
        );
        for (const provider of ["firecrawl", "browse-ai", "scrapegraphai"])
            assert.ok(
                links.some((link) => link.endsWith(`/modules/${provider}`)),
            );
    }));

for (const [provider, count] of [
    ["firecrawl", 1],
    ["browse-ai", 3],
    ["scrapegraphai", 3],
]) {
    test(
        `${provider}: demo runs through queue and exports labeled simulated data`,
        { timeout: 60000 },
        async (t) =>
            withPage(t, async (page) => {
                await page.goto(`http://127.0.0.1:8012/modules/${provider}`);
                assert.equal(
                    await page.$eval("#mode", (input) => input.value),
                    "demo",
                );
                await Promise.all([
                    page.waitForNavigation(),
                    page.click("#submit-button"),
                ]);
                await page.waitForFunction(
                    () =>
                        document
                            .querySelector("#run-status")
                            ?.textContent.includes("Completado"),
                    { timeout: 30000 },
                );
                assert.match(
                    await page.$eval(
                        "#execution-mode",
                        (element) => element.textContent,
                    ),
                    /DATOS SIMULADOS/,
                );
                assert.equal(
                    await page.$$eval(
                        ".results-table tbody tr",
                        (rows) => rows.length,
                    ),
                    count,
                );
                const exported = await page.evaluate(async () =>
                    (
                        await fetch(
                            document.querySelector('a[href$="/export/json"]')
                                .href,
                        )
                    ).json(),
                );
                assert.equal(exported.mode, "demo");
                assert.equal(exported.simulated, true);
                assert.equal(exported.provider, provider);
                assert.equal(exported.records.length, count);
                const csv = await page.evaluate(async () =>
                    (
                        await fetch(
                            document.querySelector('a[href$="/export/csv"]')
                                .href,
                        )
                    ).text(),
                );
                assert.match(csv, /_mode,_provider/);
                assert.match(csv, new RegExp(`demo,${provider}`));
                if (provider === "browse-ai")
                    assert.match(
                        await page.$eval(
                            "dl",
                            (element) => element.textContent,
                        ),
                        /demo-task-/,
                    );
            }),
    );
}

test("Firecrawl JSON controls produce structured demo rows", async (t) =>
    withPage(t, async (page) => {
        await page.goto("http://127.0.0.1:8012/modules/firecrawl");
        assert.equal(
            await page.$eval("#json-options", (element) => element.hidden),
            true,
        );
        await page.select("#format", "json");
        assert.equal(
            await page.$eval("#json-options", (element) => element.hidden),
            false,
        );
        await Promise.all([
            page.waitForNavigation(),
            page.click("#submit-button"),
        ]);
        await page.waitForFunction(
            () =>
                document
                    .querySelector("#run-status")
                    ?.textContent.includes("Completado"),
            { timeout: 30000 },
        );
        assert.equal(
            await page.$$eval(".results-table tbody tr", (rows) => rows.length),
            3,
        );
    }));

test("live mode without credentials reports validation instead of running demo", async (t) =>
    withPage(t, async (page) => {
        await page.goto("http://127.0.0.1:8012/modules/firecrawl");
        await page.select("#mode", "live");
        assert.match(
            await page.$eval("#mode-note", (element) => element.textContent),
            /API real/,
        );
        await Promise.all([
            page.waitForNavigation(),
            page.click("#submit-button"),
        ]);
        assert.match(
            await page.$eval(
                '[role="alert"]',
                (element) => element.textContent,
            ),
            /API key/,
        );
        assert.equal(await page.$eval("#mode", (input) => input.value), "live");
        assert.equal(
            await page.$eval("#submit-button", (button) => button.disabled),
            false,
        );
    }));

test("invalid JSON remains editable after validation", async (t) =>
    withPage(t, async (page) => {
        await page.goto("http://127.0.0.1:8012/modules/browse-ai");
        await page.$eval("#input_parameters_json", (input) => {
            input.value = "{ invalid";
        });
        await Promise.all([
            page.waitForNavigation(),
            page.click("#submit-button"),
        ]);
        assert.match(
            await page.$eval(
                '[role="alert"]',
                (element) => element.textContent,
            ),
            /JSON/,
        );
        assert.equal(
            await page.$eval("#input_parameters_json", (input) => input.value),
            "{ invalid",
        );
    }));

test("mobile pages retain navigation without horizontal overflow", async (t) =>
    withPage(t, async (page) => {
        await page.goto("http://127.0.0.1:8012/modules/firecrawl");
        await Promise.all([
            page.waitForNavigation(),
            page.click("#submit-button"),
        ]);
        await page.waitForFunction(
            () =>
                document
                    .querySelector("#run-status")
                    ?.textContent.includes("Completado"),
            { timeout: 30000 },
        );
        await page.setViewport({ width: 390, height: 844 });
        for (const path of [
            "/",
            "/modules/firecrawl",
            "/modules/browse-ai",
            "/modules/scrapegraphai",
        ]) {
            await page.goto(`http://127.0.0.1:8012${path}`);
            const layout = await page.evaluate(() => ({
                width: innerWidth,
                scroll: document.documentElement.scrollWidth,
                overflow: [...document.querySelectorAll("body *")]
                    .filter(
                        (element) =>
                            element.getBoundingClientRect().right >
                            innerWidth + 1,
                    )
                    .map((element) => ({
                        tag: element.tagName,
                        class: element.className,
                        right: element.getBoundingClientRect().right,
                    }))
                    .slice(0, 12),
            }));
            assert.ok(
                layout.scroll <= layout.width,
                JSON.stringify({ path, ...layout }),
            );
            assert.equal(
                await page.$$eval(".sidebar nav a", (links) => links.length),
                4,
            );
        }
    }));
