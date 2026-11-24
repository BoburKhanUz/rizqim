// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rates2.dart';

part 'data62.g.dart';

@JsonSerializable()
class Data62 {
  const Data62({required this.rates, required this.channels});

  factory Data62.fromJson(Map<String, Object?> json) => _$Data62FromJson(json);

  final List<Rates2> rates;
  final List<dynamic> channels;

  Map<String, Object?> toJson() => _$Data62ToJson(this);
}
