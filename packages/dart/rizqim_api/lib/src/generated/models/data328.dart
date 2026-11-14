// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots10.dart';

part 'data328.g.dart';

@JsonSerializable()
class Data328 {
  const Data328({required this.slots});

  factory Data328.fromJson(Map<String, Object?> json) =>
      _$Data328FromJson(json);

  final List<Slots10> slots;

  Map<String, Object?> toJson() => _$Data328ToJson(this);
}
