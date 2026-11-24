// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data16.g.dart';

@JsonSerializable()
class Data16 {
  const Data16({required this.features});

  factory Data16.fromJson(Map<String, Object?> json) => _$Data16FromJson(json);

  final Map<String, bool> features;

  Map<String, Object?> toJson() => _$Data16ToJson(this);
}
