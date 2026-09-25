#!/usr/bin/env -S PYTHONDONTWRITEBYTECODE=1 python3

import argparse
import datetime
import pathlib
import sys


cDestinationFolder = pathlib.Path("/var/www/mytube-videos")


def fCreateParser():
  vParser = argparse.ArgumentParser(
    description="Create symlinks in /var/www/mytube-videos for every file in a folder."
  )
  vParser.add_argument(
    "pSourceFolder",
    help="Source folder to scan recursively."
  )
  return vParser


def fValidateSourceFolder(pSourceFolder):
  vSourceFolder = pathlib.Path(pSourceFolder).expanduser().resolve()
  if not vSourceFolder.exists():
    raise FileNotFoundError(f"The source folder does not exist: {vSourceFolder}")
  if not vSourceFolder.is_dir():
    raise NotADirectoryError(f"The source path is not a folder: {vSourceFolder}")
  if (
    vSourceFolder == cDestinationFolder
    or cDestinationFolder in vSourceFolder.parents
    or vSourceFolder in cDestinationFolder.parents
  ):
    raise ValueError("The source folder and the MyOwnTube destination folder cannot overlap.")
  return vSourceFolder


def fGenerateDatedName(pFilePath):
  vDate = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
  if pFilePath.suffix:
    return f"{pFilePath.stem}_{vDate}{pFilePath.suffix}"
  return f"{pFilePath.name}_{vDate}"


def fGetSymlinkPath(pFilePath):
  vSymlinkPath = cDestinationFolder / pFilePath.name
  if not vSymlinkPath.exists() and not vSymlinkPath.is_symlink():
    return vSymlinkPath

  vDatedName = fGenerateDatedName(pFilePath)
  vSymlinkPath = cDestinationFolder / vDatedName
  if not vSymlinkPath.exists() and not vSymlinkPath.is_symlink():
    return vSymlinkPath

  vCounter = 1
  while True:
    vDatedPath = pathlib.Path(vDatedName)
    if vDatedPath.suffix:
      vFinalName = f"{vDatedPath.stem}_{vCounter}{vDatedPath.suffix}"
    else:
      vFinalName = f"{vDatedPath.name}_{vCounter}"

    vSymlinkPath = cDestinationFolder / vFinalName
    if not vSymlinkPath.exists() and not vSymlinkPath.is_symlink():
      return vSymlinkPath

    vCounter += 1


def fCreateSymlinks(pSourceFolder):
  cDestinationFolder.mkdir(parents=True, exist_ok=True)
  vCreatedTotal = 0
  vSkippedTotal = 0

  for vFilePath in sorted(pSourceFolder.rglob("*")):
    if not vFilePath.is_file():
      continue

    vSourcePath = vFilePath.resolve()
    vSymlinkPath = fGetSymlinkPath(vFilePath)

    try:
      vSymlinkPath.symlink_to(vSourcePath)
      vCreatedTotal += 1
      print(f"Created: {vSymlinkPath} -> {vSourcePath}")
    except OSError as vError:
      vSkippedTotal += 1
      print(f"Error: could not create {vSymlinkPath}: {vError}", file=sys.stderr)

  return vCreatedTotal, vSkippedTotal


def fMain():
  vParser = fCreateParser()
  vArgs = vParser.parse_args()

  try:
    vSourceFolder = fValidateSourceFolder(vArgs.pSourceFolder)
    vCreatedTotal, vSkippedTotal = fCreateSymlinks(vSourceFolder)
  except (FileNotFoundError, NotADirectoryError, PermissionError, OSError, ValueError) as vError:
    print(f"Error: {vError}", file=sys.stderr)
    return 1

  print(f"Symlinks created: {vCreatedTotal}")
  if vSkippedTotal:
    print(f"Symlinks skipped because of errors: {vSkippedTotal}", file=sys.stderr)
    return 1

  return 0


if __name__ == "__main__":
  sys.exit(fMain())
