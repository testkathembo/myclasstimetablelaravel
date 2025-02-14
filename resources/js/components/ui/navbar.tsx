import { Link } from "@inertiajs/react";
import { LogOut, User } from "lucide-react";

interface NavbarProps {
  user: {
    code: string;
  };
}

const Navbar = ({ user }: NavbarProps) => {
  return (
    <nav className="bg-blue-800 text-white p-4 shadow-md flex justify-between items-center">
      <div className="flex items-center space-x-2">
        <img src="/images/strathmore.png" alt="Logo" className="h-10 w-10 rounded-full" />
        <h2 className="text-lg font-bold">My Class Timetable</h2>
      </div>

      <div className="flex items-center space-x-6">
        <div className="flex flex-col text-right">
          <span className="text-xs text-gray-300">{user.code}</span>
        </div>

        <Link href="/profile" className="flex items-center space-x-2 hover:text-gray-300">
          <User className="h-5 w-5" />
          <span>Profile</span>
        </Link>

        <Link href="/logout" method="post" as="button" className="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-white flex items-center space-x-2">
          <LogOut className="h-5 w-5" />
          <span>Logout</span>
        </Link>
      </div>
    </nav>
  );
};

export default Navbar;