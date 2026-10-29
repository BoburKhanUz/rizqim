// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_daily_menu_rotation_request_body.g.dart';

@JsonSerializable()
class V1OwnerDailyMenuRotationRequestBody {
  const V1OwnerDailyMenuRotationRequestBody({
    required this.productIds,
    this.branchId,
  });

  factory V1OwnerDailyMenuRotationRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerDailyMenuRotationRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'product_ids')
  final List<int> productIds;

  Map<String, Object?> toJson() =>
      _$V1OwnerDailyMenuRotationRequestBodyToJson(this);
}
