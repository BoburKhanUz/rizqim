// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'queue28.g.dart';

@JsonSerializable()
class Queue28 {
  const Queue28({this.size, this.oldestAt});

  factory Queue28.fromJson(Map<String, Object?> json) =>
      _$Queue28FromJson(json);

  final int? size;
  @JsonKey(name: 'oldest_at')
  final DateTime? oldestAt;

  Map<String, Object?> toJson() => _$Queue28ToJson(this);
}
