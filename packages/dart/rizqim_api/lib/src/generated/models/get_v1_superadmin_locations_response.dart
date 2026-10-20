// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data60.dart';

part 'get_v1_superadmin_locations_response.g.dart';

@JsonSerializable()
class GetV1SuperadminLocationsResponse {
  const GetV1SuperadminLocationsResponse({
    required this.success,
    required this.data,
  });

  factory GetV1SuperadminLocationsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1SuperadminLocationsResponseFromJson(json);

  final bool success;
  final Data60 data;

  Map<String, Object?> toJson() =>
      _$GetV1SuperadminLocationsResponseToJson(this);
}
