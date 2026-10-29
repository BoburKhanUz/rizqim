// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_daily_menu_copy_request_body.g.dart';

@JsonSerializable()
class V1OwnerDailyMenuCopyRequestBody {
  const V1OwnerDailyMenuCopyRequestBody({
    this.branchId,
    this.date,
    this.fromDate,
  });

  factory V1OwnerDailyMenuCopyRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerDailyMenuCopyRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final DateTime? date;
  @JsonKey(name: 'from_date')
  final DateTime? fromDate;

  Map<String, Object?> toJson() =>
      _$V1OwnerDailyMenuCopyRequestBodyToJson(this);
}
