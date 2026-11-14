// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data142.dart';

part 'delete_v1_owner_pos_banquets_packages_package_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerPosBanquetsPackagesPackageResponse {
  const DeleteV1OwnerPosBanquetsPackagesPackageResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerPosBanquetsPackagesPackageResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerPosBanquetsPackagesPackageResponseFromJson(json);

  final bool success;
  final String message;
  final Data142 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerPosBanquetsPackagesPackageResponseToJson(this);
}
