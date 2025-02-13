import { Link } from "@inertiajs/react";
import { Home, Settings, User } from "lucide-react"; // Icons
import { Button } from "@/components/ui/button";

const Sidebar = () => {
  return (
    <div className="h-full w-64 bg-blue-900 text-white shadow-lg flex flex-col p-4">
      
      {/* Navigation Menu */}
      <nav className="flex-1">
        <ul className="space-y-2">
          <li>
            <Link href="/dashboard">
              <Button variant="ghost" className="w-full flex items-center space-x-2 text-white hover:bg-blue-700">
                <Home className="h-5 w-5" />
                <span>Dashboard</span>
              </Button>
            </Link>
          </li>
          <li>
            <Link href="/profile">
              <Button variant="ghost" className="w-full flex items-center space-x-2 text-white hover:bg-blue-700">
                <User className="h-5 w-5" />
                <span>Profile</span>
              </Button>
            </Link>
          </li>
          <li>
            <Link href="/settings">
              <Button variant="ghost" className="w-full flex items-center space-x-2 text-white hover:bg-blue-700">
                <Settings className="h-5 w-5" />
                <span>Settings</span>
              </Button>
            </Link>
          </li>
        </ul>
      </nav>

    </div>
  );
};

export default Sidebar;
