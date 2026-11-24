// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'preferences2.dart';

part 'data21.g.dart';

@JsonSerializable()
class Data21 {
  const Data21({required this.preferences});

  factory Data21.fromJson(Map<String, Object?> json) => _$Data21FromJson(json);

  final List<Preferences2> preferences;

  Map<String, Object?> toJson() => _$Data21ToJson(this);
}
