// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'queue.dart';
import 'operations.dart';

part 'v1_pos_sync_request_body.g.dart';

@JsonSerializable()
class V1PosSyncRequestBody {
  const V1PosSyncRequestBody({
    required this.operations,
    this.sentAt,
    this.queue,
  });

  factory V1PosSyncRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosSyncRequestBodyFromJson(json);

  @JsonKey(name: 'sent_at')
  final DateTime? sentAt;
  final Queue? queue;
  final List<Operations> operations;

  Map<String, Object?> toJson() => _$V1PosSyncRequestBodyToJson(this);
}
