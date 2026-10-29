// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'queue24.g.dart';

@JsonSerializable()
class Queue24 {
  const Queue24({this.size, this.oldestAt});

  factory Queue24.fromJson(Map<String, Object?> json) =>
      _$Queue24FromJson(json);

  final int? size;
  @JsonKey(name: 'oldest_at')
  final DateTime? oldestAt;

  Map<String, Object?> toJson() => _$Queue24ToJson(this);
}
