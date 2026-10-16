import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { api } from "../services/api";
export default function AdminLogin() {
  const [email,setEmail]=useState(""); const [password,setPassword]=useState("");
  const [error,setError]=useState(""); const [busy,setBusy]=useState(false); const navigate=useNavigate();
  async function handleSubmit(event) {
    event.preventDefault(); setError(""); setBusy(true);
    try { await api("/admin/login",{method:"POST",body:JSON.stringify({email,password})}); navigate("/admin",{replace:true}); }
    catch(err) { setError(err.message || "Unable to sign in. Check the API connection."); }
    finally { setBusy(false); }
  }
  return <main className="admin-auth-page"><form className="admin-auth-card" onSubmit={handleSubmit}>
    <Link className="admin-brand" to="/">↗ onecar<span>.</span></Link>
    <p className="admin-eyebrow">SECURE ADMIN ACCESS</p><h1>Welcome back.</h1>
    <p className="admin-muted">Sign in with your authorized administrator account.</p>
    {error && <div className="admin-error" role="alert">{error}</div>}
    <label htmlFor="admin-email">Administrator email</label>
    <input id="admin-email" type="email" autoComplete="username" value={email} onChange={e=>setEmail(e.target.value)} required />
    <label htmlFor="admin-password">Password</label>
    <input id="admin-password" type="password" autoComplete="current-password" value={password} onChange={e=>setPassword(e.target.value)} required />
    <button className="admin-primary" disabled={busy}>{busy?"Signing in…":"Sign in to dashboard →"}</button>
    <Link className="admin-back-link" to="/">← Return to OneCar website</Link>
    <small>Admin access is verified by the PHP backend. There is no public admin registration.</small>
  </form></main>;
}
