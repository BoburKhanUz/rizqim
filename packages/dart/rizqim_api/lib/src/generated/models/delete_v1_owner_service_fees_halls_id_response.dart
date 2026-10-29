// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data126.dart';

part 'delete_v1_owner_service_fees_halls_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerServiceFeesHallsIdResponse {
  const DeleteV1OwnerServiceFeesHallsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerServiceFeesHallsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerServiceFeesHallsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data126 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerServiceFeesHallsIdResponseToJson(this);
}
