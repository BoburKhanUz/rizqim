// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data129.dart';

part 'patch_v1_owner_products_id_pos_service_response.g.dart';

@JsonSerializable()
class PatchV1OwnerProductsIdPosServiceResponse {
  const PatchV1OwnerProductsIdPosServiceResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1OwnerProductsIdPosServiceResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PatchV1OwnerProductsIdPosServiceResponseFromJson(json);

  final bool success;
  final String message;
  final Data129 data;

  Map<String, Object?> toJson() =>
      _$PatchV1OwnerProductsIdPosServiceResponseToJson(this);
}
