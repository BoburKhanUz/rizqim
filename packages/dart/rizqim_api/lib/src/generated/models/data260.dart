// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots4.dart';

part 'data260.g.dart';

@JsonSerializable()
class Data260 {
  const Data260({required this.slots});

  factory Data260.fromJson(Map<String, Object?> json) =>
      _$Data260FromJson(json);

  final List<Slots4> slots;

  Map<String, Object?> toJson() => _$Data260ToJson(this);
}
