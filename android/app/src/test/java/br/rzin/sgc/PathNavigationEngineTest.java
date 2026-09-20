package br.rzin.sgc;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertNull;

import org.junit.Test;

public class PathNavigationEngineTest {
    private static final String BASE_URL = "https://sgc.rzin.com.br";

    @Test
    public void backReturnsToPreviousPathWithoutReaddingCurrentPath() {
        PathNavigationEngine engine = new PathNavigationEngine();
        engine.recordNavigation(BASE_URL + "/dashboard");
        engine.recordNavigation(BASE_URL + "/profile");

        assertEquals(BASE_URL + "/dashboard", engine.beginBackNavigation());
        engine.completePageLoad(BASE_URL + "/dashboard");

        assertEquals(1, engine.depth());
        assertNull(engine.beginBackNavigation());
    }

    @Test
    public void returningToExistingPathCollapsesTheLoop() {
        PathNavigationEngine engine = new PathNavigationEngine();
        engine.recordNavigation(BASE_URL + "/a");
        engine.recordNavigation(BASE_URL + "/b");
        engine.recordNavigation(BASE_URL + "/a");
        engine.recordNavigation(BASE_URL + "/b");

        assertEquals(2, engine.depth());
        assertEquals(BASE_URL + "/a", engine.beginBackNavigation());
        engine.completePageLoad(BASE_URL + "/a");
        assertNull(engine.beginBackNavigation());
    }

    @Test
    public void queryStringRepresentsASeparateNavigationState() {
        PathNavigationEngine engine = new PathNavigationEngine();
        engine.recordNavigation(BASE_URL + "/orders?page=1");
        engine.recordNavigation(BASE_URL + "/orders?page=2");

        assertEquals(2, engine.depth());
        assertEquals(BASE_URL + "/orders?page=1", engine.beginBackNavigation());
    }

    @Test
    public void fragmentsDoNotCreateArtificialDepth() {
        PathNavigationEngine engine = new PathNavigationEngine();
        engine.recordNavigation(BASE_URL + "/report#top");
        engine.recordNavigation(BASE_URL + "/report#details");

        assertEquals(1, engine.depth());
        assertNull(engine.beginBackNavigation());
    }

    @Test
    public void redirectAfterBackReplacesTheExpectedDestination() {
        PathNavigationEngine engine = new PathNavigationEngine();
        engine.recordNavigation(BASE_URL + "/login");
        engine.recordNavigation(BASE_URL + "/dashboard");

        assertEquals(BASE_URL + "/login", engine.beginBackNavigation());
        engine.completePageLoad(BASE_URL + "/dashboard");

        assertEquals(1, engine.depth());
        assertNull(engine.beginBackNavigation());
    }

    @Test
    public void repeatedBackIsIgnoredWhileThePreviousPageIsLoading() {
        PathNavigationEngine engine = new PathNavigationEngine();
        engine.recordNavigation(BASE_URL + "/a");
        engine.recordNavigation(BASE_URL + "/b");
        engine.recordNavigation(BASE_URL + "/c");

        assertEquals(BASE_URL + "/b", engine.beginBackNavigation());
        assertNull(engine.beginBackNavigation());
        assertEquals(2, engine.depth());
    }

    @Test
    public void invalidAndNonWebUrlsAreIgnored() {
        PathNavigationEngine engine = new PathNavigationEngine();
        engine.recordNavigation("about:blank");
        engine.recordNavigation("not a url");

        assertEquals(0, engine.depth());
    }
}
