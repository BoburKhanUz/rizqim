// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_branch_variants_request_body.g.dart';

@JsonSerializable()
class V1OwnerBranchVariantsRequestBody {
  const V1OwnerBranchVariantsRequestBody({
    required this.branchId,
    required this.productId,
    required this.productVariantId,
    required this.isActive,
    this.hiddenReason,
    this.sortOrder,
  });

  factory V1OwnerBranchVariantsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBranchVariantsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_variant_id')
  final int productVariantId;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'hidden_reason')
  final String? hiddenReason;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;

  Map<String, Object?> toJson() =>
      _$V1OwnerBranchVariantsRequestBodyToJson(this);
}
