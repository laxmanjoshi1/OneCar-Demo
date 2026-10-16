import { Navigate, Route, Routes } from "react-router-dom";
import HomePage from "./pages/HomePage.jsx";
import AdminLogin from "./pages/AdminLogin.jsx";
import AdminDashboard from "./pages/AdminDashboard.jsx";
export default function App() {
  return <Routes>
    <Route path="/" element={<HomePage />} />
    <Route path="/admin/login" element={<AdminLogin />} />
    <Route path="/admin/*" element={<AdminDashboard />} />
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes>;
}
