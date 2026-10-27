// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data15.dart';

part 'get_v1_admin_search_response.g.dart';

@JsonSerializable()
class GetV1AdminSearchResponse {
  const GetV1AdminSearchResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1AdminSearchResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1AdminSearchResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data15> data;

  Map<String, Object?> toJson() => _$GetV1AdminSearchResponseToJson(this);
}
