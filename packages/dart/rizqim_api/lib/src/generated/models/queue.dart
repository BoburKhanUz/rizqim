// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'queue.g.dart';

@JsonSerializable()
class Queue {
  const Queue({this.size, this.oldestAt});

  factory Queue.fromJson(Map<String, Object?> json) => _$QueueFromJson(json);

  final int? size;
  @JsonKey(name: 'oldest_at')
  final DateTime? oldestAt;

  Map<String, Object?> toJson() => _$QueueToJson(this);
}
