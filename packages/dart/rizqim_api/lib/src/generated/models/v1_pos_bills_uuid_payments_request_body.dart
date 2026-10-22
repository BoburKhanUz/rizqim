// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tenders.dart';

part 'v1_pos_bills_uuid_payments_request_body.g.dart';

@JsonSerializable()
class V1PosBillsUuidPaymentsRequestBody {
  const V1PosBillsUuidPaymentsRequestBody({
    required this.tenders,
    this.approvalToken,
    this.shiftUuid,
    this.version,
  });

  factory V1PosBillsUuidPaymentsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBillsUuidPaymentsRequestBodyFromJson(json);

  final List<Tenders> tenders;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBillsUuidPaymentsRequestBodyToJson(this);
}
