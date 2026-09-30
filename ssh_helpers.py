import sys
import os
import paramiko


def get_creds():
    host = os.environ.get("SSH_HOST")
    user = os.environ.get("SSH_USER")
    password = os.environ.get("SSH_PASS")
    if not (host and user and password):
        print("FATAL: set SSH_HOST / SSH_USER / SSH_PASS env vars first "
              "(values are in ./.credentials, раздел ssh)", file=sys.stderr)
        sys.exit(1)
    return host, user, password


def ssh_exec(cmd, outfile=None):
    host, user, password = get_creds()
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(host, port=22, username=user, password=password, timeout=15)
    stdin, stdout, stderr = client.exec_command(cmd, timeout=90)
    out = stdout.read()
    err = stderr.read()
    client.close()
    if outfile:
        with open(outfile, "wb") as f:
            f.write(out)
        sys.stderr.buffer.write(err)
        print(f"wrote {len(out)} bytes to {outfile}")
    else:
        sys.stdout.buffer.write(out)
        sys.stderr.buffer.write(err)


def sftp_upload(remote_dir, pairs):
    host, user, password = get_creds()
    transport = paramiko.Transport((host, 22))
    transport.connect(username=user, password=password)
    sftp = paramiko.SFTPClient.from_transport(transport)
    try:
        sftp.mkdir(remote_dir)
    except IOError:
        pass
    for local, remote_name in pairs:
        remote_path = remote_dir.rstrip("/") + "/" + remote_name
        sftp.put(local, remote_path)
        print(f"uploaded {local} -> {remote_path}")
    sftp.close()
    transport.close()


def sftp_cleanup(remote_dir):
    """Удаляет все файлы внутри remote_dir и саму папку (без rm -rf, файл за файлом)."""
    host, user, password = get_creds()
    transport = paramiko.Transport((host, 22))
    transport.connect(username=user, password=password)
    sftp = paramiko.SFTPClient.from_transport(transport)
    for f in sftp.listdir(remote_dir):
        sftp.remove(remote_dir.rstrip("/") + "/" + f)
        print("removed", f)
    sftp.rmdir(remote_dir)
    print("rmdir OK")
    sftp.close()
    transport.close()


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)

    action = sys.argv[1]
    if action == "exec":
        cmd = sys.argv[2]
        outfile = sys.argv[3] if len(sys.argv) > 3 else None
        ssh_exec(cmd, outfile)
    elif action == "upload":
        remote_dir = sys.argv[2]
        pairs = [tuple(x.split("::")) for x in sys.argv[3:]]
        sftp_upload(remote_dir, pairs)
    elif action == "cleanup":
        sftp_cleanup(sys.argv[2])
    else:
        print(f"Unknown action: {action}")
        sys.exit(1)
