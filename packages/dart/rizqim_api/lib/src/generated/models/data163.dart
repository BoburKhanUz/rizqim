// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'unsigned_tills2.dart';

part 'data163.g.dart';

@JsonSerializable()
class Data163 {
  const Data163({
    required this.enforce,
    required this.signingTills,
    required this.unsignedTills,
  });

  factory Data163.fromJson(Map<String, Object?> json) =>
      _$Data163FromJson(json);

  final bool enforce;
  @JsonKey(name: 'signing_tills')
  final int signingTills;
  @JsonKey(name: 'unsigned_tills')
  final List<UnsignedTills2> unsignedTills;

  Map<String, Object?> toJson() => _$Data163ToJson(this);
}
