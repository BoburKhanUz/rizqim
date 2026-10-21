// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_auto_guest_items_request_body.g.dart';

@JsonSerializable()
class V1OwnerAutoGuestItemsRequestBody {
  const V1OwnerAutoGuestItemsRequestBody({
    required this.branchId,
    required this.productVariantId,
    required this.quantityPerGuest,
    this.hallId,
    this.isActive,
    this.sortOrder,
  });

  factory V1OwnerAutoGuestItemsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerAutoGuestItemsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'product_variant_id')
  final int productVariantId;
  @JsonKey(name: 'quantity_per_guest')
  final num quantityPerGuest;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;

  Map<String, Object?> toJson() =>
      _$V1OwnerAutoGuestItemsRequestBodyToJson(this);
}
