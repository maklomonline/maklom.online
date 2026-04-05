/**
 * clockTimer — Wall-clock based timer for Go games with byo-yomi support.
 *
 * Design principles:
 * - Uses Date.now() as reference (not cumulative setInterval drift).
 * - On each tick, computes elapsed = (Date.now() - turnRefMs) / 1000.
 * - Handles main-time → byo-yomi transition automatically.
 * - syncFromEvent() resets the reference to now() on every server event.
 */
export function clockTimer(
    gameId,
    initBlack, initWhite,
    clockType,
    byoyomiSeconds,
    initBlackPeriods, initWhitePeriods,
    initColor,
    lastMoveTs,
    initialStatus,
) {
    const gameAlreadyOver = initialStatus === 'finished' || initialStatus === 'aborted';

    // Wall-clock reference: when did the active player's current turn begin?
    // lastMoveTs is a Unix timestamp (seconds) from the server.
    const turnRefMs = gameAlreadyOver
        ? Date.now()
        : (lastMoveTs > 0 ? lastMoveTs * 1000 : Date.now());

    return {
        clockType,
        byoyomiSeconds: byoyomiSeconds || 30,
        activeColor: initColor,
        interval: null,
        timeoutDispatched: false,

        // Server-authoritative snapshots (set by syncFromEvent)
        blackTimeSnap:    initBlack,
        whiteTimeSnap:    initWhite,
        blackPeriodsSnap: initBlackPeriods,
        whitePeriodsSnap: initWhitePeriods,

        // Wall-clock reference for the active player's current turn.
        turnRefMs,

        // Derived display values — updated by tick()
        blackTime:       initBlack,
        whiteTime:       initWhite,
        blackPeriods:    initBlackPeriods,
        whitePeriods:    initWhitePeriods,
        blackByoyomiLeft: byoyomiSeconds || 30,
        whiteByoyomiLeft: byoyomiSeconds || 30,

        // ── Lifecycle ─────────────────────────────────────────────────────────

        init() {
            // Run first tick immediately so the display is correct on load.
            this.tick();

            if (!gameAlreadyOver) {
                this.startTick();

                window.addEventListener('clock-sync', (e) => {
                    this.syncFromEvent(e.detail, e.detail.nextColor);
                });
                window.addEventListener('game-over', () => {
                    clearInterval(this.interval);
                });
            }

            if (!window.Echo) return;

            window.Echo.private(`game.${gameId}`)
                .listen('MoveMade', (e) => {
                    this.syncFromEvent(e, e.nextColor);
                })
                .listen('PlayerPassed', (e) => {
                    const next = e.nextColor ?? (e.color === 'black' ? 'white' : 'black');
                    this.syncFromEvent(e, next);
                })
                .listen('GameEnded', () => {
                    clearInterval(this.interval);
                });
        },

        // ── Sync from server event ────────────────────────────────────────────

        syncFromEvent(e, nextColor) {
            // Update server snapshots.
            if (e.blackTimeLeft    !== undefined) this.blackTimeSnap    = e.blackTimeLeft;
            if (e.whiteTimeLeft    !== undefined) this.whiteTimeSnap    = e.whiteTimeLeft;
            if (e.blackPeriodsLeft !== undefined) this.blackPeriodsSnap = e.blackPeriodsLeft;
            if (e.whitePeriodsLeft !== undefined) this.whitePeriodsSnap = e.whitePeriodsLeft;

            // New turn starts NOW (client side).
            this.turnRefMs = Date.now();
            this.timeoutDispatched = false;

            if (nextColor) this.activeColor = nextColor;

            // Refresh display immediately.
            this.tick();
        },

        // ── Tick (wall-clock based) ───────────────────────────────────────────

        startTick() {
            clearInterval(this.interval);
            // 200 ms interval for smooth display; no drift because we use Date.now().
            this.interval = setInterval(() => this.tick(), 200);
        },

        tick() {
            const elapsedSec = Math.max(0, (Date.now() - this.turnRefMs) / 1000);
            const byo = this.byoyomiSeconds;

            for (const color of ['black', 'white']) {
                const timeSnap    = color === 'black' ? this.blackTimeSnap    : this.whiteTimeSnap;
                const periodsSnap = color === 'black' ? this.blackPeriodsSnap : this.whitePeriodsSnap;

                if (color !== this.activeColor) {
                    // Non-active player: keep snapshot values unchanged.
                    this[`${color}Time`]       = timeSnap;
                    this[`${color}Periods`]    = periodsSnap;
                    this[`${color}ByoyomiLeft`] = byo;
                    continue;
                }

                const remaining = timeSnap - elapsedSec;

                if (remaining > 0) {
                    // Still in main time.
                    this[`${color}Time`]       = Math.ceil(remaining);
                    this[`${color}Periods`]    = periodsSnap;
                    this[`${color}ByoyomiLeft`] = byo;
                } else if (this.clockType === 'byoyomi' && periodsSnap > 0) {
                    // Main time exhausted — count down inside byo-yomi periods.
                    const byoElapsed       = -remaining;                  // seconds since main time hit 0
                    const intoThisPeriod   = byoElapsed % byo;            // seconds into the current period
                    const periodsConsumed  = Math.floor(byoElapsed / byo);
                    const periodsRemaining = Math.max(0, periodsSnap - periodsConsumed);

                    this[`${color}Time`]       = 0;
                    this[`${color}Periods`]    = periodsRemaining;
                    this[`${color}ByoyomiLeft`] = Math.ceil(byo - intoThisPeriod);
                } else {
                    // All time and periods exhausted.
                    this[`${color}Time`]       = 0;
                    this[`${color}Periods`]    = 0;
                    this[`${color}ByoyomiLeft`] = 0;

                    if (!this.timeoutDispatched) {
                        this.timeoutDispatched = true;
                        window.dispatchEvent(new CustomEvent('clock-timeout', { detail: { color } }));
                    }
                }
            }
        },

        // ── Display helpers ───────────────────────────────────────────────────

        /**
         * Returns the value to show in the clock face for the given color.
         * In byo-yomi it shows the period countdown; in main time the main time.
         */
        getDisplayTime(color) {
            const mainTime = color === 'black' ? this.blackTime : this.whiteTime;
            if (mainTime > 0) return mainTime;
            if (this.clockType === 'byoyomi') {
                const periods = color === 'black' ? this.blackPeriods : this.whitePeriods;
                if (periods > 0) {
                    return color === 'black' ? this.blackByoyomiLeft : this.whiteByoyomiLeft;
                }
            }
            return 0;
        },

        formatTime(seconds) {
            seconds = Math.max(0, Math.floor(seconds));
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        },
    };
}
