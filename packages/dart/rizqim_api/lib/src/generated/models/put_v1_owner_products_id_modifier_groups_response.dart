// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data117.dart';

part 'put_v1_owner_products_id_modifier_groups_response.g.dart';

@JsonSerializable()
class PutV1OwnerProductsIdModifierGroupsResponse {
  const PutV1OwnerProductsIdModifierGroupsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerProductsIdModifierGroupsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerProductsIdModifierGroupsResponseFromJson(json);

  final bool success;
  final String message;
  final Data117 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerProductsIdModifierGroupsResponseToJson(this);
}
