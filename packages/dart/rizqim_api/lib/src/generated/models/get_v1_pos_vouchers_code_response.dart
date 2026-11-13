// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data300.dart';

part 'get_v1_pos_vouchers_code_response.g.dart';

@JsonSerializable()
class GetV1PosVouchersCodeResponse {
  const GetV1PosVouchersCodeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosVouchersCodeResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosVouchersCodeResponseFromJson(json);

  final bool success;
  final String message;
  final Data300 data;

  Map<String, Object?> toJson() => _$GetV1PosVouchersCodeResponseToJson(this);
}
