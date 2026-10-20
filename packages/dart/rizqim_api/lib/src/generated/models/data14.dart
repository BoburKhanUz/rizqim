// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data14.g.dart';

@JsonSerializable()
class Data14 {
  const Data14({required this.features});

  factory Data14.fromJson(Map<String, Object?> json) => _$Data14FromJson(json);

  final Map<String, bool> features;

  Map<String, Object?> toJson() => _$Data14ToJson(this);
}
