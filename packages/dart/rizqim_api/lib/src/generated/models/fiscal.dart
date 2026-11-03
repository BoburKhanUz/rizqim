// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'single.dart';
import 'split.dart';

part 'fiscal.g.dart';

@JsonSerializable()
class Fiscal {
  const Fiscal({required this.single, required this.split});

  factory Fiscal.fromJson(Map<String, Object?> json) => _$FiscalFromJson(json);

  final List<Single> single;
  final List<Split> split;

  Map<String, Object?> toJson() => _$FiscalToJson(this);
}
