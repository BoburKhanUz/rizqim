// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rates2.dart';

part 'data61.g.dart';

@JsonSerializable()
class Data61 {
  const Data61({required this.rates, required this.channels});

  factory Data61.fromJson(Map<String, Object?> json) => _$Data61FromJson(json);

  final List<Rates2> rates;
  final List<dynamic> channels;

  Map<String, Object?> toJson() => _$Data61ToJson(this);
}
