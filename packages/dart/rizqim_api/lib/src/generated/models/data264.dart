// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data264.g.dart';

@JsonSerializable()
class Data264 {
  const Data264({required this.received});

  factory Data264.fromJson(Map<String, Object?> json) =>
      _$Data264FromJson(json);

  final bool received;

  Map<String, Object?> toJson() => _$Data264ToJson(this);
}
