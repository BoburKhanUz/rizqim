// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'weight_basis.dart';

part 'v1_owner_products_id_pos_service_request_body.g.dart';

@JsonSerializable()
class V1OwnerProductsIdPosServiceRequestBody {
  const V1OwnerProductsIdPosServiceRequestBody({
    this.quickMultipliers,
    this.waitMinutes,
    this.weightMode,
  });

  factory V1OwnerProductsIdPosServiceRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerProductsIdPosServiceRequestBodyFromJson(json);

  @JsonKey(name: 'quick_multipliers')
  final List<int>? quickMultipliers;
  @JsonKey(name: 'wait_minutes')
  final int? waitMinutes;
  @JsonKey(name: 'weight_mode')
  final WeightBasis? weightMode;

  Map<String, Object?> toJson() =>
      _$V1OwnerProductsIdPosServiceRequestBodyToJson(this);
}
