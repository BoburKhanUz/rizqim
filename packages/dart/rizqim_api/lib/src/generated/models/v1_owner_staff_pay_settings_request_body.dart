// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_pay_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffPaySettingsRequestBody {
  const V1OwnerStaffPaySettingsRequestBody({
    required this.period,
    this.branchId,
  });

  factory V1OwnerStaffPaySettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerStaffPaySettingsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;

  /// App\Domain\Pos\StaffPay\PayPeriod: day, week, half_month, month.
  final String period;

  Map<String, Object?> toJson() =>
      _$V1OwnerStaffPaySettingsRequestBodyToJson(this);
}
