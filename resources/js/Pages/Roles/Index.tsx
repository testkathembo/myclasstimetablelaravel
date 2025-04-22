"use client"

import React, { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface Role {
  id: number
  name: string
  permissions: { id: number; name: string }[]
}

interface Permission {
  id: number
  name: string
}

// Changed component name from Roles to Index
const Index = () => {
  const { roles: initialRoles, permissions: allPermissions } = usePage().props as {
    roles: Role[]
    permissions: Permission[]
  }

  const [roles, setRoles] = useState<Role[]>(initialRoles)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"create" | "edit" | "delete" | "">("")
  const [selectedRole, setSelectedRole] = useState<Role | null>(null)
  const [formState, setFormState] = useState({ name: "", permissions: [] as number[] })

  useEffect(() => {
    if (modalType === "edit" && selectedRole) {
      setFormState({
        name: selectedRole.name,
        permissions: selectedRole.permissions.map((p) => p.id),
      })
    } else {
      setFormState({ name: "", permissions: [] })
    }
  }, [modalType, selectedRole])

  const handleOpenModal = (type: "create" | "edit" | "delete", role: Role | null = null) => {
    setModalType(type)
    setSelectedRole(role)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setModalType("")
    setSelectedRole(null)
    setFormState({ name: "", permissions: [] })
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (modalType === "create") {
      router.post("/roles", formState, {
        onSuccess: () => {
          alert("Role created successfully")
          setIsModalOpen(false)
        },
        onError: (errors) => {
          alert("Failed to create role: " + JSON.stringify(errors))
        },
      })
    } else if (modalType === "edit" && selectedRole) {
      router.put(`/roles/${selectedRole.id}`, formState, {
        onSuccess: () => {
          alert("Role updated successfully")
          setIsModalOpen(false)
        },
        onError: (errors) => {
          alert("Failed to update role: " + JSON.stringify(errors))
        },
      })
    } else if (modalType === "delete" && selectedRole) {
      router.delete(`/roles/${selectedRole.id}`, {
        onSuccess: () => {
          alert("Role deleted successfully")
          setIsModalOpen(false)
        },
        onError: (errors) => {
          alert("Failed to delete role: " + JSON.stringify(errors))
        },
      })
    }
  }

  const togglePermission = (permissionId: number) => {
    setFormState((prev) => ({
      ...prev,
      permissions: prev.permissions.includes(permissionId)
        ? prev.permissions.filter((id) => id !== permissionId)
        : [...prev.permissions, permissionId],
    }))
  }

  return (
    <AuthenticatedLayout>
      <Head title="Roles Management" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-6">Roles Management</h1>
        <button 
          onClick={() => handleOpenModal("create")} 
          className="px-4 py-2 mb-4 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          + Create Role
        </button>
        
        <table className="w-full border border-gray-200">
          <thead>
            <tr className="bg-gray-100">
              <th className="px-4 py-2 text-left">#</th>
              <th className="px-4 py-2 text-left">Name</th>
              <th className="px-4 py-2 text-left">Permissions</th>
              <th className="px-4 py-2 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            {roles.map((role, index) => (
              <tr key={role.id} className="hover:bg-gray-50 border-b">
                <td className="px-4 py-2">{index + 1}</td>
                <td className="px-4 py-2">{role.name}</td>
                <td className="px-4 py-2">
                  <div className="flex flex-wrap gap-1">
                    {role.permissions.map((permission) => (
                      <span
                        key={permission.id}
                        className="inline-block bg-blue-100 text-blue-700 text-xs font-medium px-2 py-1 rounded"
                      >
                        {permission.name}
                      </span>
                    ))}
                  </div>
                </td>
                <td className="px-4 py-2">
                  <button
                    onClick={() => handleOpenModal("edit", role)}
                    className="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 mr-2"
                  >
                    Edit
                  </button>
                  <button
                    onClick={() => handleOpenModal("delete", role)}
                    className="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {isModalOpen && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div className="bg-white p-6 rounded-lg shadow-lg w-96 max-h-[90vh] overflow-y-auto">
              <div className="mb-4">
                <h2 className="text-xl font-semibold">
                  {modalType === "create" && "Create Role"}
                  {modalType === "edit" && "Edit Role"}
                  {modalType === "delete" && "Delete Role"}
                </h2>
              </div>
              
              {modalType === "delete" ? (
                <>
                  <p className="text-gray-700 mb-4">
                    Are you sure you want to delete the role <strong>{selectedRole?.name}</strong>?
                  </p>
                  <div className="flex justify-end space-x-2 mt-4">
                    <button
                      onClick={handleSubmit}
                      className="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600"
                    >
                      Delete
                    </button>
                    <button
                      onClick={handleCloseModal}
                      className="px-4 py-2 bg-gray-400 text-white rounded hover:bg-gray-500"
                    >
                      Cancel
                    </button>
                  </div>
                </>
              ) : (
                <form onSubmit={handleSubmit}>
                  <div className="mb-4">
                    <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                      Role Name
                    </label>
                    <input
                      id="name"
                      type="text"
                      value={formState.name}
                      onChange={(e) => setFormState({ ...formState, name: e.target.value })}
                      required
                      className="w-full border rounded px-3 py-2 mt-1"
                    />
                  </div>
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Permissions
                    </label>
                    <div className="h-72 overflow-y-auto border rounded p-3 mt-2">
                      <div className="grid grid-cols-2 gap-2">
                        {allPermissions.map((permission) => (
                          <div key={permission.id} className="flex items-center">
                            <input
                              type="checkbox"
                              id={`permission-${permission.id}`}
                              checked={formState.permissions.includes(permission.id)}
                              onChange={() => togglePermission(permission.id)}
                              className="mr-2"
                            />
                            <label htmlFor={`permission-${permission.id}`} className="text-sm">
                              {permission.name}
                            </label>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                  <div className="flex justify-end space-x-2 mt-4">
                    <button
                      type="submit"
                      className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                      {modalType === "create" && "Create"}
                      {modalType === "edit" && "Update"}
                    </button>
                    <button
                      type="button"
                      onClick={handleCloseModal}
                      className="px-4 py-2 bg-gray-400 text-white rounded hover:bg-gray-500"
                    >
                      Cancel
                    </button>
                  </div>
                </form>
              )}
            </div>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}
export default Index