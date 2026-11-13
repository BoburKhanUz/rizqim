// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr_provider.dart';

part 'v1_owner_pos_qr_payments_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosQrPaymentsRequestBody {
  const V1OwnerPosQrPaymentsRequestBody({
    this.providers,
    this.defaultProvider,
    this.expiryMinutes,
    this.branchId,
  });

  factory V1OwnerPosQrPaymentsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerPosQrPaymentsRequestBodyFromJson(json);

  final List<QrProvider>? providers;
  @JsonKey(name: 'default_provider')
  final QrProvider? defaultProvider;
  @JsonKey(name: 'expiry_minutes')
  final int? expiryMinutes;

  /// The branch (a branch-bound caller: their own when omitted).
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosQrPaymentsRequestBodyToJson(this);
}
