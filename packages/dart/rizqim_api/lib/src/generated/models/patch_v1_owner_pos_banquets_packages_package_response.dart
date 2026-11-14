// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data143.dart';

part 'patch_v1_owner_pos_banquets_packages_package_response.g.dart';

@JsonSerializable()
class PatchV1OwnerPosBanquetsPackagesPackageResponse {
  const PatchV1OwnerPosBanquetsPackagesPackageResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerPosBanquetsPackagesPackageResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1OwnerPosBanquetsPackagesPackageResponseFromJson(json);

  final bool success;
  final String message;
  final Data143 data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerPosBanquetsPackagesPackageResponseToJson(this);
}
