// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data113.dart';

part 'patch_v1_owner_printers_id_response.g.dart';

@JsonSerializable()
class PatchV1OwnerPrintersIdResponse {
  const PatchV1OwnerPrintersIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerPrintersIdResponse.fromJson(Map<String, Object?> json) =>
      _$PatchV1OwnerPrintersIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data113 data;

  Map<String, Object?> toJson() => _$PatchV1OwnerPrintersIdResponseToJson(this);
}
