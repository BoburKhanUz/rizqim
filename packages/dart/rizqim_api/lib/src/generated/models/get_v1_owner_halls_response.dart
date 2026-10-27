// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data91.dart';

part 'get_v1_owner_halls_response.g.dart';

@JsonSerializable()
class GetV1OwnerHallsResponse {
  const GetV1OwnerHallsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerHallsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerHallsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data91> data;

  Map<String, Object?> toJson() => _$GetV1OwnerHallsResponseToJson(this);
}
