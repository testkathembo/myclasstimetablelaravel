import { Link } from "@inertiajs/react";
import { LogOut, User } from "lucide-react"; // Icons

const Navbar = () => {
  return (
    <nav className="bg-blue-800 text-white p-4 shadow-md flex justify-between items-center">
      
      {/* Brand Logo */}
      <div className="flex items-center space-x-2">
        <img src="/images/strathmore.png" alt="Logo" className="h-10 w-10 rounded-full" />
        <h2 className="text-lg font-bold">Dashboard</h2>
      </div>

      {/* User Profile & Logout */}
      <div className="flex items-center space-x-4">
        <Link href="/profile" className="flex items-center space-x-2">
          <User className="h-5 w-5" />
          <span>Profile</span>
        </Link>

        <Link href="/logout" method="post" as="button" className="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-white">
          <LogOut className="h-5 w-5" />
        </Link>
      </div>

    </nav>
  );
};

export default Navbar;
