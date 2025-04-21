"use client"

import React, { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogFooter } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"

interface Role {
  id: number
  name: string
  permissions: { id: number; name: string }[]
}

interface Permission {
  id: number
  name: string
}

const Roles = () => {
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
    <>
      <Head title="Roles Management" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Roles Management</h1>
        <Button onClick={() => handleOpenModal("create")} className="bg-green-500 hover:bg-green-600 mb-4">
          + Create Role
        </Button>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>#</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Permissions</TableHead>
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {roles.map((role, index) => (
              <TableRow key={role.id}>
                <TableCell>{index + 1}</TableCell>
                <TableCell>{role.name}</TableCell>
                <TableCell>
                  {role.permissions.map((permission) => (
                    <span key={permission.id} className="text-sm text-gray-600">
                      {permission.name}
                    </span>
                  ))}
                </TableCell>
                <TableCell>
                  <Button
                    onClick={() => handleOpenModal("edit", role)}
                    className="bg-yellow-500 hover:bg-yellow-600 text-white mr-2"
                  >
                    Edit
                  </Button>
                  <Button
                    onClick={() => handleOpenModal("delete", role)}
                    className="bg-red-500 hover:bg-red-600 text-white"
                  >
                    Delete
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        {isModalOpen && (
          <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
            <DialogContent>
              <DialogHeader>
                <h2 className="text-xl font-semibold">
                  {modalType === "create" && "Create Role"}
                  {modalType === "edit" && "Edit Role"}
                  {modalType === "delete" && "Delete Role"}
                </h2>
              </DialogHeader>
              {modalType === "delete" ? (
                <p>Are you sure you want to delete the role "{selectedRole?.name}"?</p>
              ) : (
                <form onSubmit={handleSubmit}>
                  <div className="mb-4">
                    <Label htmlFor="name">Role Name</Label>
                    <Input
                      id="name"
                      value={formState.name}
                      onChange={(e) => setFormState({ ...formState, name: e.target.value })}
                      required
                    />
                  </div>
                  <div className="mb-4">
                    <Label>Permissions</Label>
                    <div className="grid grid-cols-2 gap-2">
                      {allPermissions.map((permission) => (
                        <div key={permission.id} className="flex items-center">
                          <Checkbox
                            id={`permission-${permission.id}`}
                            checked={formState.permissions.includes(permission.id)}
                            onCheckedChange={() => togglePermission(permission.id)}
                          />
                          <Label htmlFor={`permission-${permission.id}`} className="ml-2">
                            {permission.name}
                          </Label>
                        </div>
                      ))}
                    </div>
                  </div>
                  <DialogFooter>
                    <Button type="submit" className="bg-blue-500 hover:bg-blue-600">
                      {modalType === "create" && "Create"}
                      {modalType === "edit" && "Update"}
                    </Button>
                    <Button type="button" onClick={handleCloseModal} className="bg-gray-400 hover:bg-gray-500">
                      Cancel
                    </Button>
                  </DialogFooter>
                </form>
              )}
              {modalType === "delete" && (
                <DialogFooter>
                  <Button onClick={handleSubmit} className="bg-red-500 hover:bg-red-600">
                    Delete
                  </Button>
                  <Button onClick={handleCloseModal} className="bg-gray-400 hover:bg-gray-500">
                    Cancel
                  </Button>
                </DialogFooter>
              )}
            </DialogContent>
          </Dialog>
        )}
      </div>
    </>
  )
}

export default Roles
