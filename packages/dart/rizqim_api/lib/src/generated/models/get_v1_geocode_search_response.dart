// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data38.dart';

part 'get_v1_geocode_search_response.g.dart';

@JsonSerializable()
class GetV1GeocodeSearchResponse {
  const GetV1GeocodeSearchResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1GeocodeSearchResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1GeocodeSearchResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data38> data;

  Map<String, Object?> toJson() => _$GetV1GeocodeSearchResponseToJson(this);
}
