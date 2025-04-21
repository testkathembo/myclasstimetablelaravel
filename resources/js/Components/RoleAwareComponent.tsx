import type React from "react"
import { usePage } from "@inertiajs/react"

interface PageProps {
  role: string
  permissions: string[]
}

interface RoleAwareComponentProps {
  requiredRole?: string
  requiredPermission?: string
  children: React.ReactNode
  fallback?: React.ReactNode
}

export default function RoleAwareComponent({
  requiredRole,
  requiredPermission,
  children,
  fallback = null,
}: RoleAwareComponentProps) {
  const { role = "", permissions = [] } = usePage().props as PageProps

  // For debugging
  console.log("Current user role:", role)
  console.log("Required role:", requiredRole)
  console.log("User permissions:", permissions)

  const hasRequiredRole = !requiredRole || role === requiredRole
  const hasRequiredPermission = !requiredPermission || permissions.includes(requiredPermission)

  if (hasRequiredRole && hasRequiredPermission) {
    return <>{children}</>
  }

  return <>{fallback}</>
}
