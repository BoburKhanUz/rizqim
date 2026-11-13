// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data187.dart';

part 'get_v1_pos_bill_print_requests_response.g.dart';

@JsonSerializable()
class GetV1PosBillPrintRequestsResponse {
  const GetV1PosBillPrintRequestsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBillPrintRequestsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBillPrintRequestsResponseFromJson(json);

  final bool success;
  final String message;
  final Data187 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBillPrintRequestsResponseToJson(this);
}
