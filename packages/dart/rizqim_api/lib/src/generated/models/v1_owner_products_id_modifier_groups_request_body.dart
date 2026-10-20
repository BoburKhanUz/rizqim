// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'groups.dart';

part 'v1_owner_products_id_modifier_groups_request_body.g.dart';

@JsonSerializable()
class V1OwnerProductsIdModifierGroupsRequestBody {
  const V1OwnerProductsIdModifierGroupsRequestBody({required this.groups});

  factory V1OwnerProductsIdModifierGroupsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerProductsIdModifierGroupsRequestBodyFromJson(json);

  final List<Groups> groups;

  Map<String, Object?> toJson() =>
      _$V1OwnerProductsIdModifierGroupsRequestBodyToJson(this);
}
