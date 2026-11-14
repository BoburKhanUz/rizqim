// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data136.dart';

part 'get_v1_owner_pos_banquets_halls_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosBanquetsHallsResponse {
  const GetV1OwnerPosBanquetsHallsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosBanquetsHallsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerPosBanquetsHallsResponseFromJson(json);

  final bool success;
  final String message;
  final Data136 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosBanquetsHallsResponseToJson(this);
}
