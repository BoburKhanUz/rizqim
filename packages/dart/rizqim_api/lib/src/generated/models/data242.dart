// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event12.dart';

part 'data242.g.dart';

@JsonSerializable()
class Data242 {
  const Data242({required this.event});

  factory Data242.fromJson(Map<String, Object?> json) =>
      _$Data242FromJson(json);

  final Event12 event;

  Map<String, Object?> toJson() => _$Data242ToJson(this);
}
