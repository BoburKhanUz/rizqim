// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots10.dart';

part 'data335.g.dart';

@JsonSerializable()
class Data335 {
  const Data335({required this.slots});

  factory Data335.fromJson(Map<String, Object?> json) =>
      _$Data335FromJson(json);

  final List<Slots10> slots;

  Map<String, Object?> toJson() => _$Data335ToJson(this);
}
