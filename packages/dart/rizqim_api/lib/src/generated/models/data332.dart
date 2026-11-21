// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots10.dart';

part 'data332.g.dart';

@JsonSerializable()
class Data332 {
  const Data332({required this.slots});

  factory Data332.fromJson(Map<String, Object?> json) =>
      _$Data332FromJson(json);

  final List<Slots10> slots;

  Map<String, Object?> toJson() => _$Data332ToJson(this);
}
