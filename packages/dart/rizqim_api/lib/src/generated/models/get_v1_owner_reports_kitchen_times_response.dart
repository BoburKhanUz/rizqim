// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data145.dart';

part 'get_v1_owner_reports_kitchen_times_response.g.dart';

@JsonSerializable()
class GetV1OwnerReportsKitchenTimesResponse {
  const GetV1OwnerReportsKitchenTimesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerReportsKitchenTimesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerReportsKitchenTimesResponseFromJson(json);

  final bool success;
  final String message;
  final Data145 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerReportsKitchenTimesResponseToJson(this);
}
