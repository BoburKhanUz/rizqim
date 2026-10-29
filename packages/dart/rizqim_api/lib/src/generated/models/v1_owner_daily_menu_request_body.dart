// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_daily_menu_request_body.g.dart';

@JsonSerializable()
class V1OwnerDailyMenuRequestBody {
  const V1OwnerDailyMenuRequestBody({
    required this.productIds,
    this.branchId,
    this.date,
  });

  factory V1OwnerDailyMenuRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerDailyMenuRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final DateTime? date;
  @JsonKey(name: 'product_ids')
  final List<int> productIds;

  Map<String, Object?> toJson() => _$V1OwnerDailyMenuRequestBodyToJson(this);
}
