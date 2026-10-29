// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bank3.dart';
import 'rizqim3.dart';

part 'unmatched3.g.dart';

@JsonSerializable()
class Unmatched3 {
  const Unmatched3({required this.bank, required this.rizqim});

  factory Unmatched3.fromJson(Map<String, Object?> json) =>
      _$Unmatched3FromJson(json);

  final List<Bank3> bank;
  final List<Rizqim3> rizqim;

  Map<String, Object?> toJson() => _$Unmatched3ToJson(this);
}
