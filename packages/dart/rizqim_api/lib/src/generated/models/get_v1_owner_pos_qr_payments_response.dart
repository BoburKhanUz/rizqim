// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data132.dart';

part 'get_v1_owner_pos_qr_payments_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosQrPaymentsResponse {
  const GetV1OwnerPosQrPaymentsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosQrPaymentsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerPosQrPaymentsResponseFromJson(json);

  final bool success;
  final String message;
  final Data132 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosQrPaymentsResponseToJson(this);
}
