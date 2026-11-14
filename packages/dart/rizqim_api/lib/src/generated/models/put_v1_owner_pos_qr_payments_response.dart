// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data156.dart';

part 'put_v1_owner_pos_qr_payments_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosQrPaymentsResponse {
  const PutV1OwnerPosQrPaymentsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosQrPaymentsResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerPosQrPaymentsResponseFromJson(json);

  final bool success;
  final String message;
  final Data156 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerPosQrPaymentsResponseToJson(this);
}
