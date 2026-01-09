"use client";

import { useEffect, useMemo, useState } from "react";

const LS_TOKEN_KEY = "notion_token_v1";

function isRecord(v) {
  return typeof v === "object" && v !== null && !Array.isArray(v);
}

function errorMessage(e) {
  if (e instanceof Error) return e.message;
  if (typeof e === "string") return e;
  return "Unknown error";
}

async function apiFetch(path, init = {}) {
  const url = path;
  const headers = {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (init.headers) new Headers(init.headers).forEach((v, k) => (headers[k] = v));
  if (init.token) headers.Authorization = `Bearer ${init.token}`;

  const res = await fetch(url, { ...init, headers });
  const text = await res.text();
  let data = text;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    // noop
  }
  if (!res.ok) {
    const msg =
      isRecord(data) && "message" in data ? String(data.message) : `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data;
}

function clsx(...parts) {
  return parts.filter(Boolean).join(" ");
}

function Card({ title, subtitle, children, className }) {
  return (
    <section
      className={clsx(
        "rounded-2xl border border-white/10 bg-zinc-950/60 p-5 shadow-[0_0_0_1px_rgba(255,255,255,0.06)] backdrop-blur",
        className,
      )}
    >
      <div className="flex flex-col gap-1">
        <h2 className="text-sm font-semibold tracking-wide text-zinc-100">{title}</h2>
        {subtitle ? (
          <p className="text-xs leading-relaxed text-zinc-400">{subtitle}</p>
        ) : null}
      </div>
      <div className="mt-4">{children}</div>
    </section>
  );
}

function Field({ label, hint, children }) {
  return (
    <label className="block">
      <div className="mb-1 flex items-baseline justify-between gap-3">
        <div className="text-xs font-medium text-zinc-200">{label}</div>
        {hint ? <div className="text-[11px] text-zinc-500">{hint}</div> : null}
      </div>
      {children}
    </label>
  );
}

function Input(props) {
  return (
    <input
      {...props}
      className={clsx(
        "w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600",
        "focus:outline-none focus:ring-2 focus:ring-indigo-500/60",
        props.className,
      )}
    />
  );
}

function Textarea(props) {
  return (
    <textarea
      {...props}
      className={clsx(
        "w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600",
        "focus:outline-none focus:ring-2 focus:ring-indigo-500/60",
        props.className,
      )}
    />
  );
}

function Button({ variant = "primary", className, ...props }) {
  const base =
    "inline-flex items-center justify-center rounded-xl px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-indigo-500/60 disabled:opacity-50";
  const styles =
    variant === "primary"
      ? "bg-white text-black hover:bg-zinc-100"
      : variant === "ghost"
        ? "border border-white/10 bg-transparent text-zinc-200 hover:bg-white/5"
        : "border border-white/10 bg-black/20 text-zinc-200 hover:bg-white/5";
  return <button {...props} className={clsx(base, styles, className)} />;
}

function formatDt(iso) {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return String(iso);
  return d.toLocaleString();
}

export default function Home() {
  const [token, setToken] = useState(() => {
    try {
      return localStorage.getItem(LS_TOKEN_KEY) ?? "";
    } catch {
      return "";
    }
  });
  const [user, setUser] = useState(null);
  const [log, setLog] = useState("");

  const [email, setEmail] = useState("dev@example.com");
  const [password, setPassword] = useState("password123");
  const [name, setName] = useState("Dev");

  const [taskTitle, setTaskTitle] = useState("First task");
  const [taskDueAt, setTaskDueAt] = useState("");

  const [noteTitle, setNoteTitle] = useState("Note title");
  const [noteBody, setNoteBody] = useState("");
  const [noteTags, setNoteTags] = useState("work,idea");

  const [eventTitle, setEventTitle] = useState("Meeting");
  const [eventStartAt, setEventStartAt] = useState("");
  const [eventEndAt, setEventEndAt] = useState("");
  const [eventRemind, setEventRemind] = useState(10);

  const [tgType, setTgType] = useState("private");
  const [tgChatId, setTgChatId] = useState("");
  const [tgTargets, setTgTargets] = useState([]);
  const [activeTab, setActiveTab] = useState("dashboard");
  const [eventLogs, setEventLogs] = useState([]);
  const [eventLogsMeta, setEventLogsMeta] = useState({ current_page: 1, last_page: 1 });
  const [eventLogsLoading, setEventLogsLoading] = useState(false);
  const [selectedLogId, setSelectedLogId] = useState(null);

  useEffect(() => {
    if (token) localStorage.setItem(LS_TOKEN_KEY, token);
    else localStorage.removeItem(LS_TOKEN_KEY);
  }, [token]);

  async function refreshTelegramTargets(t = token) {
    if (!t) {
      setTgTargets([]);
      return;
    }
    try {
      const res = await apiFetch("/api/telegram-targets", { method: "GET", token: t });
      setTgTargets(res.telegram_targets || []);
    } catch (e) {
      setLog(`telegram targets: ${errorMessage(e)}`);
    }
  }

  useEffect(() => {
    refreshTelegramTargets();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  async function refreshEventLogs({ page = 1, append = false } = {}) {
    if (!token) {
      setEventLogs([]);
      setEventLogsMeta({ current_page: 1, last_page: 1 });
      return;
    }
    try {
      setEventLogsLoading(true);
      const res = await apiFetch(`/api/event-logs?page=${page}`, { method: "GET", token });
      const items = res.data || [];
      setEventLogsMeta({
        current_page: res.current_page || page,
        last_page: res.last_page || page,
      });
      setEventLogs((prev) => (append ? [...prev, ...items] : items));
    } catch (e) {
      setLog(`event logs: ${errorMessage(e)}`);
    } finally {
      setEventLogsLoading(false);
    }
  }

  useEffect(() => {
    refreshEventLogs({ page: 1, append: false });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  const prettyUser = useMemo(() => {
    return user ? JSON.stringify(user, null, 2) : "";
  }, [user]);

  const primaryChatId = useMemo(() => {
    const t = (tgTargets || []).find((x) => x.enabled && x.type === "private");
    return t ? t.chat_id : null;
  }, [tgTargets]);

  const recentLogs = useMemo(() => {
    return (eventLogs || []).slice(0, 6);
  }, [eventLogs]);

  async function handleRegister() {
    try {
      setLog("register...");
      const res = await apiFetch("/api/auth/register", {
        method: "POST",
        body: JSON.stringify({ name, email, password }),
      });
      setToken(res.token);
      setUser(res.user);
      setLog("register: ok");
    } catch (e) {
      setLog(`register: ${errorMessage(e)}`);
    }
  }

  async function handleLogin() {
    try {
      setLog("login...");
      const res = await apiFetch("/api/auth/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });
      setToken(res.token);
      setUser(res.user);
      await refreshTelegramTargets(res.token);
      setLog("login: ok");
    } catch (e) {
      setLog(`login: ${errorMessage(e)}`);
    }
  }

  async function handleMe() {
    try {
      setLog("me...");
      const res = await apiFetch("/api/auth/me", {
        method: "GET",
        token,
      });
      setUser(res.user);
      await refreshTelegramTargets(token);
      setLog("me: ok");
    } catch (e) {
      setLog(`me: ${errorMessage(e)}`);
    }
  }

  async function handleLogout() {
    try {
      setLog("logout...");
      await apiFetch("/api/auth/logout", { method: "POST", token });
      setToken("");
      setUser(null);
      setLog("logout: ok");
    } catch (e) {
      setLog(`logout: ${errorMessage(e)}`);
    }
  }

  async function createTask() {
    try {
      setLog("create task...");
      let dueAt = null;
      if (taskDueAt) {
        const d = new Date(taskDueAt);
        if (Number.isNaN(d.getTime())) {
          throw new Error(
            "due_at noto‘g‘ri. Sana/vaqt tanlang (masalan: 2026-01-10 14:30).",
          );
        }
        dueAt = d.toISOString();
      }
      await apiFetch("/api/tasks", {
        method: "POST",
        token,
        body: JSON.stringify({
          title: taskTitle,
          due_at: dueAt,
        }),
      });
      setLog("task: created (Telegram enabled bo‘lsa xabar ketadi)");
      await refreshEventLogs({ page: 1, append: false });
    } catch (e) {
      setLog(`task: ${errorMessage(e)}`);
    }
  }

  async function createNote() {
    try {
      setLog("create note...");
      await apiFetch("/api/notes", {
        method: "POST",
        token,
        body: JSON.stringify({
          title: noteTitle,
          body: noteBody || null,
          tags: noteTags
            .split(",")
            .map((s) => s.trim())
            .filter(Boolean),
        }),
      });
      setLog("note: created (Telegram enabled bo‘lsa xabar ketadi)");
      await refreshEventLogs({ page: 1, append: false });
    } catch (e) {
      setLog(`note: ${errorMessage(e)}`);
    }
  }

  async function createEvent() {
    try {
      setLog("create calendar event...");
      const start = new Date(eventStartAt);
      if (Number.isNaN(start.getTime())) {
        throw new Error(
          "start_at noto‘g‘ri. Sana/vaqt tanlang (masalan: 2026-01-10 14:30).",
        );
      }
      let endAt = null;
      if (eventEndAt) {
        const end = new Date(eventEndAt);
        if (Number.isNaN(end.getTime())) {
          throw new Error(
            "end_at noto‘g‘ri. Sana/vaqt tanlang (masalan: 2026-01-10 15:30).",
          );
        }
        endAt = end.toISOString();
      }
      await apiFetch("/api/calendar-events", {
        method: "POST",
        token,
        body: JSON.stringify({
          title: eventTitle,
          start_at: start.toISOString(),
          end_at: endAt,
          remind_before_minute: Number(eventRemind),
        }),
      });
      setLog("calendar-event: created (Telegram enabled bo‘lsa xabar ketadi)");
      await refreshEventLogs({ page: 1, append: false });
    } catch (e) {
      setLog(`calendar-event: ${errorMessage(e)}`);
    }
  }

  async function addTelegramTarget() {
    try {
      setLog("add telegram target...");
      await apiFetch("/api/telegram-targets", {
        method: "POST",
        token,
        body: JSON.stringify({
          type: tgType,
          chat_id: tgChatId,
          enabled: true,
        }),
      });
      await refreshTelegramTargets(token);
      await refreshEventLogs({ page: 1, append: false });
      setLog("telegram target: saved");
    } catch (e) {
      setLog(`telegram target: ${errorMessage(e)}`);
    }
  }

  async function deleteTelegramTarget(id) {
    try {
      setLog(`delete telegram target #${id}...`);
      await apiFetch(`/api/telegram-targets/${id}`, {
        method: "DELETE",
        token,
      });
      await refreshTelegramTargets(token);
      await refreshEventLogs({ page: 1, append: false });
      setLog("telegram target: deleted");
    } catch (e) {
      setLog(`telegram target delete: ${errorMessage(e)}`);
    }
  }

  async function sendTelegramTest() {
    try {
      setLog("telegram test...");
      const res = await apiFetch("/api/telegram/test", {
        method: "POST",
        token,
        body: JSON.stringify({ text: "Test from Notion Mini" }),
      });
      setLog(`telegram test: ${JSON.stringify(res, null, 2)}`);
      await refreshEventLogs({ page: 1, append: false });
    } catch (e) {
      setLog(`telegram test: ${errorMessage(e)}`);
    }
  }

  return (
    <div className="min-h-screen bg-black text-zinc-100">
      <div className="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_at_top,rgba(99,102,241,0.18),transparent_55%),radial-gradient(ellipse_at_bottom,rgba(16,185,129,0.10),transparent_55%)]" />

      <div className="relative mx-auto max-w-6xl px-4 py-6 lg:px-6">
        {/* Topbar */}
        <div className="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-zinc-950/50 px-4 py-4 backdrop-blur lg:flex-row lg:items-center lg:justify-between">
          <div className="flex items-center gap-3">
            <div className="grid h-10 w-10 place-items-center rounded-2xl bg-white text-black">
              <span className="text-sm font-black">N</span>
            </div>
            <div>
              <div className="text-lg font-semibold leading-tight">Notion Mini</div>
              <div className="text-xs text-zinc-400">
                API: <span className="font-mono text-zinc-300">/api</span> • TZ:{" "}
                <span className="font-mono text-zinc-300">Asia/Tashkent</span>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {token ? (
              <>
                <div className="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-xs">
                  <div className="text-zinc-400">User</div>
                  <div className="font-mono text-zinc-200">
                    {user?.name || "—"} • {user?.email || "—"}
                  </div>
                </div>
                <div className="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-xs">
                  <div className="text-zinc-400">Primary chat_id</div>
                  <div className="font-mono text-zinc-200">
                    {primaryChatId || "(none)"}
                  </div>
                </div>
                <Button variant="ghost" onClick={handleLogout}>
                  Logout
                </Button>
              </>
            ) : (
              <div className="text-sm text-zinc-400">Login qilib dashboardga kiring</div>
            )}
          </div>
        </div>

        {!token ? (
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Card
              title="Auth"
              subtitle="Register/Login qiling. Token localStorage’da saqlanadi."
            >
              <div className="space-y-3">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <Field label="Name">
                    <Input value={name} onChange={(e) => setName(e.target.value)} />
                  </Field>
                  <Field label="Email">
                    <Input value={email} onChange={(e) => setEmail(e.target.value)} />
                  </Field>
                </div>
                <Field label="Password">
                  <Input
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                </Field>
                <div className="flex flex-wrap gap-2">
                  <Button onClick={handleRegister}>Register</Button>
                  <Button variant="secondary" onClick={handleLogin}>
                    Login
                  </Button>
                  <Button variant="ghost" onClick={handleMe}>
                    Me
                  </Button>
                </div>
              </div>
            </Card>

            <Card title="Status" subtitle="Agar xato bo‘lsa shu yerda ko‘rasiz.">
              <pre className="min-h-40 whitespace-pre-wrap rounded-xl border border-white/10 bg-black/30 p-3 text-xs text-zinc-200">
                {log || "(no logs)"}
              </pre>
            </Card>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-[220px_1fr]">
            {/* Sidebar */}
            <nav className="rounded-2xl border border-white/10 bg-zinc-950/50 p-2 backdrop-blur">
              {[
                ["dashboard", "Dashboard"],
                ["tasks", "Tasks"],
                ["notes", "Notes"],
                ["calendar", "Calendar"],
                ["telegram", "Telegram"],
                ["logs", "Logs"],
              ].map(([key, label]) => (
                <button
                  key={key}
                  onClick={() => setActiveTab(key)}
                  className={clsx(
                    "w-full rounded-xl px-3 py-2 text-left text-sm transition",
                    activeTab === key
                      ? "bg-white text-black"
                      : "text-zinc-200 hover:bg-white/5",
                  )}
                >
                  {label}
                </button>
              ))}
            </nav>

            {/* Main */}
            <div className="space-y-6">
              {activeTab === "dashboard" ? (
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                  <Card
                    title="Quick actions"
                    subtitle="Task/Note/Event yaratish va Telegramga yuborish."
                    className="md:col-span-3"
                  >
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                      <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                        <div className="text-xs font-semibold text-zinc-300">Task</div>
                        <div className="mt-3 space-y-3">
                          <Field label="Title">
                            <Input
                              value={taskTitle}
                              onChange={(e) => setTaskTitle(e.target.value)}
                              placeholder="title"
                            />
                          </Field>
                          <Field label="Due at" hint="optional">
                            <Input
                              value={taskDueAt}
                              onChange={(e) => setTaskDueAt(e.target.value)}
                              type="datetime-local"
                              placeholder="due_at"
                            />
                          </Field>
                          <Button onClick={createTask}>Create task</Button>
                        </div>
                      </div>

                      <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                        <div className="text-xs font-semibold text-zinc-300">Note</div>
                        <div className="mt-3 space-y-3">
                          <Field label="Title">
                            <Input
                              value={noteTitle}
                              onChange={(e) => setNoteTitle(e.target.value)}
                              placeholder="title"
                            />
                          </Field>
                          <Field label="Body" hint="optional">
                            <Textarea
                              rows={3}
                              value={noteBody}
                              onChange={(e) => setNoteBody(e.target.value)}
                              placeholder="body"
                            />
                          </Field>
                          <Field label="Tags" hint="comma-separated">
                            <Input
                              value={noteTags}
                              onChange={(e) => setNoteTags(e.target.value)}
                              placeholder="work,idea"
                            />
                          </Field>
                          <Button onClick={createNote}>Create note</Button>
                        </div>
                      </div>

                      <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                        <div className="text-xs font-semibold text-zinc-300">Calendar</div>
                        <div className="mt-3 space-y-3">
                          <Field label="Title">
                            <Input
                              value={eventTitle}
                              onChange={(e) => setEventTitle(e.target.value)}
                              placeholder="title"
                            />
                          </Field>
                          <Field label="Start at">
                            <Input
                              value={eventStartAt}
                              onChange={(e) => setEventStartAt(e.target.value)}
                              type="datetime-local"
                              placeholder="start_at"
                            />
                          </Field>
                          <Field label="End at" hint="optional">
                            <Input
                              value={eventEndAt}
                              onChange={(e) => setEventEndAt(e.target.value)}
                              type="datetime-local"
                              placeholder="end_at"
                            />
                          </Field>
                          <Field label="Remind before (min)">
                            <Input
                              type="number"
                              value={eventRemind}
                              onChange={(e) => setEventRemind(Number(e.target.value))}
                            />
                          </Field>
                          <Button onClick={createEvent}>Create event</Button>
                        </div>
                      </div>
                    </div>
                  </Card>

                  <Card title="Telegram" subtitle="Primary chat_id va targets holati.">
                    <div className="text-sm text-zinc-300">
                      Primary: <span className="font-mono">{primaryChatId || "—"}</span>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                      <Button variant="secondary" onClick={sendTelegramTest}>
                        Send test
                      </Button>
                      <Button variant="ghost" onClick={() => refreshTelegramTargets(token)}>
                        Refresh targets
                      </Button>
                    </div>
                  </Card>

                  <Card title="Recent activity" subtitle="Oxirgi event loglar (event_logs).">
                    <div className="flex items-center justify-between">
                      <div className="text-xs text-zinc-400">
                        {eventLogsLoading ? "Loading..." : `Items: ${eventLogs.length}`}
                      </div>
                      <Button
                        variant="ghost"
                        onClick={() => {
                          setActiveTab("logs");
                          refreshEventLogs({ page: 1, append: false });
                        }}
                      >
                        Open logs
                      </Button>
                    </div>
                    <div className="mt-3 space-y-2">
                      {recentLogs.length ? (
                        recentLogs.map((l) => (
                          <button
                            key={l.id}
                            onClick={() => {
                              setActiveTab("logs");
                              setSelectedLogId(l.id);
                            }}
                            className="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-left hover:bg-white/5"
                          >
                            <div className="flex items-start justify-between gap-3">
                              <div className="min-w-0">
                                <div className="truncate text-sm font-medium text-zinc-200">
                                  {l.type}
                                </div>
                                <div className="mt-0.5 text-[11px] text-zinc-500">
                                  #{l.id} • {formatDt(l.created_at)}
                                </div>
                              </div>
                              <div
                                className={clsx(
                                  "shrink-0 rounded-lg px-2 py-1 text-[11px]",
                                  l.telegram_sent_at
                                    ? "bg-emerald-500/15 text-emerald-200"
                                    : "bg-zinc-500/15 text-zinc-300",
                                )}
                              >
                                {l.telegram_sent_at ? "telegram ✓" : "telegram —"}
                              </div>
                            </div>
                          </button>
                        ))
                      ) : (
                        <div className="text-sm text-zinc-400">(no logs yet)</div>
                      )}
                    </div>
                  </Card>

                  <Card title="Auth token" subtitle="Debug uchun qisqa ko‘rinish.">
                    <div className="font-mono text-xs text-zinc-300">
                      {token ? `${token.slice(0, 12)}…` : "(none)"}
                    </div>
                  </Card>

                  <Card title="Profile (raw)" subtitle="Backend /me response.">
                    <pre className="max-h-56 overflow-auto whitespace-pre-wrap rounded-xl border border-white/10 bg-black/30 p-3 text-xs text-zinc-200">
                      {prettyUser || "(empty)"}
                    </pre>
                  </Card>
                </div>
              ) : null}

              {activeTab === "telegram" ? (
                <Card
                  title="Telegram targets"
                  subtitle="chat_id topish: botga “/start” yozing. Channel bo‘lsa bot admin bo‘lsin va chat_id -100... bo‘ladi."
                >
                  <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-[160px_1fr_140px]">
                      <Field label="Type">
                        <select
                          className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/60"
                          value={tgType}
                          onChange={(e) =>
                            setTgType(e.target.value === "channel" ? "channel" : "private")
                          }
                        >
                          <option value="private">private</option>
                          <option value="channel">channel</option>
                        </select>
                      </Field>
                      <Field label="chat_id" hint="123456789 yoki -100...">
                        <Input
                          value={tgChatId}
                          onChange={(e) => setTgChatId(e.target.value)}
                          placeholder="chat_id"
                        />
                      </Field>
                      <div className="flex items-end gap-2">
                        <Button onClick={addTelegramTarget}>Save</Button>
                        <Button variant="secondary" onClick={sendTelegramTest}>
                          Test
                        </Button>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 gap-2">
                      {tgTargets.length ? (
                        tgTargets.map((t) => (
                          <div
                            key={t.id}
                            className="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-black/20 px-3 py-2"
                          >
                            <div className="min-w-0">
                              <div className="text-[11px] text-zinc-500">
                                #{t.id} • {t.type} • enabled={String(t.enabled)}
                              </div>
                              <div className="truncate font-mono text-sm text-zinc-200">
                                {t.chat_id}
                              </div>
                            </div>
                            <Button variant="ghost" onClick={() => deleteTelegramTarget(t.id)}>
                              Delete
                            </Button>
                          </div>
                        ))
                      ) : (
                        <div className="text-sm text-zinc-400">(no targets)</div>
                      )}
                    </div>
                  </div>
                </Card>
              ) : null}

              {activeTab === "tasks" ? (
                <Card title="Create task" subtitle="Telegramga xabar ketadi (enabled target bo‘lsa).">
                  <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <Field label="Title">
                      <Input
                        value={taskTitle}
                        onChange={(e) => setTaskTitle(e.target.value)}
                        placeholder="title"
                      />
                    </Field>
                    <Field label="Due at" hint="optional">
                      <Input
                        value={taskDueAt}
                        onChange={(e) => setTaskDueAt(e.target.value)}
                        type="datetime-local"
                      />
                    </Field>
                  </div>
                  <div className="mt-4 flex gap-2">
                    <Button onClick={createTask}>Create task</Button>
                  </div>
                </Card>
              ) : null}

              {activeTab === "notes" ? (
                <Card title="Create note" subtitle="Body/tags ham Telegramga ketadi.">
                  <div className="space-y-3">
                    <Field label="Title">
                      <Input
                        value={noteTitle}
                        onChange={(e) => setNoteTitle(e.target.value)}
                        placeholder="title"
                      />
                    </Field>
                    <Field label="Body" hint="optional">
                      <Textarea
                        rows={4}
                        value={noteBody}
                        onChange={(e) => setNoteBody(e.target.value)}
                        placeholder="body"
                      />
                    </Field>
                    <Field label="Tags" hint="comma-separated">
                      <Input
                        value={noteTags}
                        onChange={(e) => setNoteTags(e.target.value)}
                        placeholder="work,idea"
                      />
                    </Field>
                    <Button onClick={createNote}>Create note</Button>
                  </div>
                </Card>
              ) : null}

              {activeTab === "calendar" ? (
                <Card title="Create calendar event" subtitle="Reminder scheduler ham ishlaydi.">
                  <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <Field label="Title" hint="Meeting">
                      <Input
                        value={eventTitle}
                        onChange={(e) => setEventTitle(e.target.value)}
                        placeholder="title"
                      />
                    </Field>
                    <Field label="Remind before (min)">
                      <Input
                        type="number"
                        value={eventRemind}
                        onChange={(e) => setEventRemind(Number(e.target.value))}
                      />
                    </Field>
                    <Field label="Start at">
                      <Input
                        value={eventStartAt}
                        onChange={(e) => setEventStartAt(e.target.value)}
                        type="datetime-local"
                      />
                    </Field>
                    <Field label="End at" hint="optional">
                      <Input
                        value={eventEndAt}
                        onChange={(e) => setEventEndAt(e.target.value)}
                        type="datetime-local"
                      />
                    </Field>
                  </div>
                  <div className="mt-4">
                    <Button onClick={createEvent}>Create event</Button>
                  </div>
                </Card>
              ) : null}

              {activeTab === "logs" ? (
                <Card title="Event logs" subtitle="event_logs jadvalidan (faqat sizniki).">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="text-xs text-zinc-400">
                      page {eventLogsMeta.current_page} / {eventLogsMeta.last_page}
                      {eventLogsLoading ? " • loading..." : ""}
                    </div>
                    <div className="flex gap-2">
                      <Button
                        variant="ghost"
                        onClick={() => refreshEventLogs({ page: 1, append: false })}
                      >
                        Refresh
                      </Button>
                      <Button
                        variant="secondary"
                        disabled={
                          eventLogsLoading ||
                          eventLogsMeta.current_page >= eventLogsMeta.last_page
                        }
                        onClick={() =>
                          refreshEventLogs({
                            page: (eventLogsMeta.current_page || 1) + 1,
                            append: true,
                          })
                        }
                      >
                        Load more
                      </Button>
                    </div>
                  </div>

                  <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1fr]">
                    <div className="space-y-2">
                      {eventLogs.length ? (
                        eventLogs.map((l) => (
                          <button
                            key={l.id}
                            onClick={() => setSelectedLogId(l.id)}
                            className={clsx(
                              "w-full rounded-xl border px-3 py-2 text-left transition",
                              selectedLogId === l.id
                                ? "border-indigo-500/40 bg-indigo-500/10"
                                : "border-white/10 bg-black/20 hover:bg-white/5",
                            )}
                          >
                            <div className="flex items-start justify-between gap-3">
                              <div className="min-w-0">
                                <div className="truncate text-sm font-medium text-zinc-100">
                                  {l.type}
                                </div>
                                <div className="mt-0.5 text-[11px] text-zinc-500">
                                  #{l.id} • entity_id: {l.entity_id} • {formatDt(l.created_at)}
                                </div>
                              </div>
                              <div
                                className={clsx(
                                  "shrink-0 rounded-lg px-2 py-1 text-[11px]",
                                  l.telegram_sent_at
                                    ? "bg-emerald-500/15 text-emerald-200"
                                    : "bg-zinc-500/15 text-zinc-300",
                                )}
                              >
                                {l.telegram_sent_at ? "telegram ✓" : "telegram —"}
                              </div>
                            </div>
                          </button>
                        ))
                      ) : (
                        <div className="text-sm text-zinc-400">(no logs)</div>
                      )}
                    </div>

                    <div>
                      {selectedLogId ? (
                        (() => {
                          const l = eventLogs.find((x) => x.id === selectedLogId);
                          if (!l) return null;
                          return (
                            <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                              <div className="flex items-start justify-between gap-3">
                                <div>
                                  <div className="text-sm font-semibold text-zinc-100">
                                    {l.type}
                                  </div>
                                  <div className="mt-1 text-[11px] text-zinc-500">
                                    #{l.id} • {formatDt(l.created_at)}
                                  </div>
                                </div>
                                <div className="text-right text-[11px] text-zinc-500">
                                  telegram_sent_at: {formatDt(l.telegram_sent_at)}
                                </div>
                              </div>

                              <div className="mt-4 grid grid-cols-2 gap-3 text-xs">
                                <div className="rounded-xl border border-white/10 bg-black/30 p-3">
                                  <div className="text-zinc-500">entity_type</div>
                                  <div className="mt-1 font-mono text-zinc-200">
                                    {l.entity_type}
                                  </div>
                                </div>
                                <div className="rounded-xl border border-white/10 bg-black/30 p-3">
                                  <div className="text-zinc-500">entity_id</div>
                                  <div className="mt-1 font-mono text-zinc-200">
                                    {l.entity_id}
                                  </div>
                                </div>
                              </div>

                              <div className="mt-4">
                                <div className="mb-2 text-xs font-semibold text-zinc-300">
                                  payload_json
                                </div>
                                <pre className="max-h-80 overflow-auto whitespace-pre-wrap rounded-xl border border-white/10 bg-black/30 p-3 text-xs text-zinc-200">
                                  {JSON.stringify(l.payload_json || {}, null, 2)}
                                </pre>
                              </div>
                            </div>
                          );
                        })()
                      ) : (
                        <div className="rounded-2xl border border-white/10 bg-black/20 p-4 text-sm text-zinc-400">
                          (select a log)
                        </div>
                      )}
                    </div>
                  </div>
                </Card>
              ) : null}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

