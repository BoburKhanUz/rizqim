// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'patch_v1_owner_brands_id_response.g.dart';

@JsonSerializable()
class PatchV1OwnerBrandsIdResponse {
  const PatchV1OwnerBrandsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerBrandsIdResponse.fromJson(Map<String, Object?> json) =>
      _$PatchV1OwnerBrandsIdResponseFromJson(json);

  final bool success;
  final String message;
  final String data;

  Map<String, Object?> toJson() => _$PatchV1OwnerBrandsIdResponseToJson(this);
}
