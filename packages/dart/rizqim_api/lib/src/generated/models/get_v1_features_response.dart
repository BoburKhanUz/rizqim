// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data36.dart';

part 'get_v1_features_response.g.dart';

@JsonSerializable()
class GetV1FeaturesResponse {
  const GetV1FeaturesResponse({required this.success, required this.data});

  factory GetV1FeaturesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1FeaturesResponseFromJson(json);

  final bool success;
  final Data36 data;

  Map<String, Object?> toJson() => _$GetV1FeaturesResponseToJson(this);
}
