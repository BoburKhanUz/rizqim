// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rizqim.g.dart';

@JsonSerializable()
class Rizqim {
  const Rizqim({required this.rrn, required this.amount, required this.kind});

  factory Rizqim.fromJson(Map<String, Object?> json) => _$RizqimFromJson(json);

  final String? rrn;
  final String amount;
  final String kind;

  Map<String, Object?> toJson() => _$RizqimToJson(this);
}
