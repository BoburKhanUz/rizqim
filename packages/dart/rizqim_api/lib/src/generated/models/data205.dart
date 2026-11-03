// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel6.dart';
import 'branch14.dart';
import 'category.dart';
import 'products.dart';
import 'availability.dart';

part 'data205.g.dart';

@JsonSerializable()
class Data205 {
  const Data205({
    required this.channel,
    required this.menuVersion,
    required this.branch,
    required this.categories,
    required this.products,
    required this.availability,
  });

  factory Data205.fromJson(Map<String, Object?> json) =>
      _$Data205FromJson(json);

  final Channel6 channel;
  @JsonKey(name: 'menu_version')
  final String menuVersion;
  final Branch14 branch;
  final List<Category> categories;
  final List<Products> products;
  final Availability availability;

  Map<String, Object?> toJson() => _$Data205ToJson(this);
}
