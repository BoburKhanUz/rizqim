// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bank2.dart';
import 'rizqim2.dart';

part 'unmatched2.g.dart';

@JsonSerializable()
class Unmatched2 {
  const Unmatched2({required this.bank, required this.rizqim});

  factory Unmatched2.fromJson(Map<String, Object?> json) =>
      _$Unmatched2FromJson(json);

  final List<Bank2> bank;
  final List<Rizqim2> rizqim;

  Map<String, Object?> toJson() => _$Unmatched2ToJson(this);
}
