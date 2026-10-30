// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items3.dart';

part 'v1_owner_kds_targets_products_request_body.g.dart';

@JsonSerializable()
class V1OwnerKdsTargetsProductsRequestBody {
  const V1OwnerKdsTargetsProductsRequestBody({
    required this.items,
    this.branchId,
  });

  factory V1OwnerKdsTargetsProductsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerKdsTargetsProductsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final List<Items3> items;

  Map<String, Object?> toJson() =>
      _$V1OwnerKdsTargetsProductsRequestBodyToJson(this);
}
