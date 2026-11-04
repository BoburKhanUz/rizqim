// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots4.dart';

part 'data258.g.dart';

@JsonSerializable()
class Data258 {
  const Data258({required this.slots});

  factory Data258.fromJson(Map<String, Object?> json) =>
      _$Data258FromJson(json);

  final List<Slots4> slots;

  Map<String, Object?> toJson() => _$Data258ToJson(this);
}
