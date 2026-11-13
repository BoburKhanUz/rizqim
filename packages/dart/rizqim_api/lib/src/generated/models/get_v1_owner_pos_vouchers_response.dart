// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data144.dart';

part 'get_v1_owner_pos_vouchers_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosVouchersResponse {
  const GetV1OwnerPosVouchersResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosVouchersResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerPosVouchersResponseFromJson(json);

  final bool success;
  final String message;
  final Data144 data;

  Map<String, Object?> toJson() => _$GetV1OwnerPosVouchersResponseToJson(this);
}
