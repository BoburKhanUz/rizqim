// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots4.dart';

part 'data253.g.dart';

@JsonSerializable()
class Data253 {
  const Data253({required this.slots});

  factory Data253.fromJson(Map<String, Object?> json) =>
      _$Data253FromJson(json);

  final List<Slots4> slots;

  Map<String, Object?> toJson() => _$Data253ToJson(this);
}
