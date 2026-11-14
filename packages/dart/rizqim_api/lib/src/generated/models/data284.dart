// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'members.dart';

part 'data284.g.dart';

@JsonSerializable()
class Data284 {
  const Data284({required this.members, required this.activeCount});

  factory Data284.fromJson(Map<String, Object?> json) =>
      _$Data284FromJson(json);

  final List<Members> members;
  @JsonKey(name: 'active_count')
  final int activeCount;

  Map<String, Object?> toJson() => _$Data284ToJson(this);
}
