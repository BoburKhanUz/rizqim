// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data106.dart';

part 'put_v1_owner_kds_targets_products_response.g.dart';

@JsonSerializable()
class PutV1OwnerKdsTargetsProductsResponse {
  const PutV1OwnerKdsTargetsProductsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerKdsTargetsProductsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerKdsTargetsProductsResponseFromJson(json);

  final bool success;
  final String message;
  final Data106 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerKdsTargetsProductsResponseToJson(this);
}
